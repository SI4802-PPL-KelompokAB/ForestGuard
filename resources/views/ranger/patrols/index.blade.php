@extends('layouts.app')

@section('title', 'Tugas patroli')

@section('content')
    <h1 class="mb-5 text-xl font-semibold text-forest-900">Tugas patroli</h1>
    @if ($activePatrol)
        <x-alert class="mb-4">Patroli sedang berlangsung. <a class="underline" href="{{ route('ranger.patrols.show', $activePatrol) }}">Lanjutkan {{ $activePatrol->code }}</a>.</x-alert>
    @endif
    <div class="grid gap-4 lg:grid-cols-2">
        @forelse ($patrols as $patrol)
            <x-card>
                <h2 class="font-semibold">{{ $patrol->code }} — {{ $patrol->forestArea->name }}</h2>
                <p>{{ $patrol->status->value }}</p>
                <p>Jadwal: {{ $patrol->scheduled_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }} {{ config('app.timezone') }}</p>
                <p>{{ $patrol->patrol_checkpoints_count }} checkpoint</p>
                <a href="{{ route('ranger.patrols.show', $patrol) }}" class="mt-3 inline-block text-forest-700 underline">{{ $patrol->status === \App\Enums\PatrolStatus::InProgress ? 'Lanjutkan patroli' : 'Buka detail' }}</a>
            </x-card>
        @empty
            <x-card>Belum ada tugas patroli untuk Anda.</x-card>
        @endforelse
    </div>
    <div class="mt-4">{{ $patrols->links() }}</div>
@endsection
