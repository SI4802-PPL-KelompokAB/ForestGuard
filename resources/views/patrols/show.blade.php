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
        @isset($conflictMessage)<x-alert type="warning">{{ $conflictMessage }}</x-alert>@endisset
        <p>Jadwal: {{ $patrol->scheduled_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }} {{ config('app.timezone') }}</p>
        <p>Jarak: {{ $patrol->total_distance_km === null ? '—' : $patrol->total_distance_km.' km' }}</p>
        <p>Mulai: {{ $patrol->started_at?->timezone(config('app.timezone'))->format('d/m/Y H:i:s') ?? '—' }}</p>
        <p>Selesai: {{ $patrol->ended_at?->timezone(config('app.timezone'))->format('d/m/Y H:i:s') ?? '—' }}</p>
        @if ($patrol->started_at)
            @php($duration = $patrol->durationSeconds())
            <p>Durasi: <span data-patrol-duration data-seconds="{{ $duration }}" data-running="{{ $patrol->status === \App\Enums\PatrolStatus::InProgress ? 'true' : 'false' }}">{{ sprintf('%02d:%02d:%02d', intdiv($duration, 3600), intdiv($duration % 3600, 60), $duration % 60) }}</span></p>
        @endif
        <h2 class="font-semibold">Rencana checkpoint</h2>
        <ol class="space-y-2">
            @foreach ($patrol->patrolCheckpoints as $planned)
                <li>{{ $planned->sequence }}. {{ $planned->checkpoint->code }} — {{ $planned->checkpoint->name }} · {{ $planned->visited_at ? 'Sudah dikunjungi' : 'Belum dikunjungi' }}</li>
            @endforeach
        </ol>
        @if (auth()->user()->isRanger())
            <div data-patrol-execution data-status="{{ $patrol->status->value }}" data-state-url="{{ route('ranger.patrols.show', $patrol) }}">
                <p data-execution-message role="alert" class="mb-3 text-sm text-red-700"></p>
                <a data-state-refresh href="{{ route('ranger.patrols.show', $patrol) }}" class="mb-3 inline-block text-forest-700 underline" hidden>Muat ulang kondisi terbaru</a>
                @if ($patrol->status === \App\Enums\PatrolStatus::Assigned)
                    @if ($activePatrol ?? null)
                        <p>Selesaikan patroli yang sedang berlangsung sebelum memulai tugas lain.</p>
                        <a class="text-forest-700 underline" href="{{ route('ranger.patrols.show', $activePatrol) }}">Lanjutkan {{ $activePatrol->code }}</a>
                    @else
                        <form method="POST" action="{{ route('ranger.patrols.start', $patrol) }}" data-patrol-mutation>
                            @csrf
                            <x-button>Mulai patroli</x-button>
                        </form>
                    @endif
                @elseif ($patrol->status === \App\Enums\PatrolStatus::InProgress)
                    <x-button type="button" data-finish-open>Akhiri patroli</x-button>
                    <dialog data-finish-dialog aria-labelledby="finish-title" class="m-auto w-[calc(100%-2rem)] max-w-md rounded-xl p-5 backdrop:bg-slate-900/50">
                        <h2 id="finish-title" class="font-semibold">Akhiri patroli ini?</h2>
                        @php($unvisitedCount = $patrol->patrolCheckpoints->whereNull('visited_at')->count())
                        <p class="my-3">Masih ada {{ $unvisitedCount }} checkpoint belum dikunjungi. Patroli tetap dapat diakhiri.</p>
                        <form method="POST" action="{{ route('ranger.patrols.finish', $patrol) }}" data-patrol-mutation class="space-y-3">
                            @csrf
                            <x-button>Ya, akhiri patroli</x-button>
                            <x-button type="button" data-finish-cancel>Batal</x-button>
                        </form>
                    </dialog>
                    <noscript><p>Aktifkan JavaScript untuk membuka konfirmasi akhir patroli.</p></noscript>
                @endif
            </div>
        @endif
    </x-card>
@endsection
