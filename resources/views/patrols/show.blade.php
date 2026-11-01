@extends('layouts.app')

@section('title', 'Detail Patroli '.$patrol->code)

@section('content')
    @php
        $isInProgress = $patrol->status === \App\Enums\PatrolStatus::InProgress;
        $isCompleted  = $patrol->status === \App\Enums\PatrolStatus::Completed;
    @endphp

    {{-- Back link --}}
    <a href="{{ route(auth()->user()->isAdmin() ? 'admin.patrols.index' : 'ranger.patrols.index') }}"
       class="mb-5 inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-forest-700 transition-colors">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
        </svg>
        Kembali ke daftar patroli
    </a>

    <div class="max-w-3xl space-y-5">

        {{-- ===== PATROL HEADER CARD ===== --}}
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            {{-- Top color bar --}}
            <div class="h-1.5 w-full {{ $isInProgress ? 'bg-emerald-500' : ($isCompleted ? 'bg-slate-300' : 'bg-amber-400') }}"></div>

            <div class="p-5 sm:p-6">
                {{-- Title row --}}
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="mb-1 font-mono text-xs text-slate-400">{{ $patrol->code }}</p>
                        <h1 class="text-xl font-bold text-slate-900">{{ $patrol->forestArea->name }}</h1>
                    </div>
                    {{-- Status Badge --}}
                    @if ($isInProgress)
                        <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1 text-sm font-semibold text-emerald-700">
                            <span class="h-2 w-2 animate-pulse rounded-full bg-emerald-500"></span>
                            Berlangsung
                        </span>
                    @elseif ($isCompleted)
                        <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-sm font-semibold text-slate-500">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            Selesai
                        </span>
                    @else
                        <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-amber-100 px-3 py-1 text-sm font-semibold text-amber-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Ditugaskan
                        </span>
                    @endif
                </div>

                @isset($conflictMessage)
                    <x-alert type="warning" class="mt-4">{{ $conflictMessage }}</x-alert>
                @endisset

                {{-- Info grid --}}
                <div class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-3">
                    {{-- Ranger --}}
                    <div class="flex flex-col gap-1 rounded-xl bg-slate-50 px-4 py-3">
                        <span class="flex items-center gap-1.5 text-xs font-medium text-slate-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            Ranger
                        </span>
                        <span class="text-sm font-semibold text-slate-700">{{ $patrol->ranger->name }}</span>
                    </div>
                    {{-- Pembuat --}}
                    <div class="flex flex-col gap-1 rounded-xl bg-slate-50 px-4 py-3">
                        <span class="flex items-center gap-1.5 text-xs font-medium text-slate-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5.121 17.804A9 9 0 0112 15a9 9 0 016.879 2.804M15 10a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            Pembuat
                        </span>
                        <span class="text-sm font-semibold text-slate-700">{{ $patrol->creator->name }}</span>
                    </div>
                    {{-- Jadwal --}}
                    <div class="flex flex-col gap-1 rounded-xl bg-slate-50 px-4 py-3">
                        <span class="flex items-center gap-1.5 text-xs font-medium text-slate-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            Jadwal
                        </span>
                        <span class="text-sm font-semibold text-slate-700">{{ $patrol->scheduled_at->timezone(config('app.timezone'))->format('d M Y, H:i') }}</span>
                    </div>
                    {{-- Jarak --}}
                    <div class="flex flex-col gap-1 rounded-xl bg-slate-50 px-4 py-3">
                        <span class="flex items-center gap-1.5 text-xs font-medium text-slate-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                            Jarak
                        </span>
                        <span class="text-sm font-semibold text-slate-700" data-patrol-distance>{{ $patrol->total_distance_km === null ? '—' : $patrol->total_distance_km.' km' }}</span>
                    </div>
                    {{-- Mulai --}}
                    <div class="flex flex-col gap-1 rounded-xl bg-slate-50 px-4 py-3">
                        <span class="flex items-center gap-1.5 text-xs font-medium text-slate-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                            Mulai
                        </span>
                        <span class="text-sm font-semibold text-slate-700">{{ $patrol->started_at?->timezone(config('app.timezone'))->format('d M Y, H:i:s') ?? '—' }}</span>
                    </div>
                    {{-- Selesai --}}
                    <div class="flex flex-col gap-1 rounded-xl bg-slate-50 px-4 py-3">
                        <span class="flex items-center gap-1.5 text-xs font-medium text-slate-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="9" y1="9" x2="15" y2="15"/><line x1="15" y1="9" x2="9" y2="15"/></svg>
                            Selesai
                        </span>
                        <span class="text-sm font-semibold text-slate-700">{{ $patrol->ended_at?->timezone(config('app.timezone'))->format('d M Y, H:i:s') ?? '—' }}</span>
                    </div>
                    {{-- Durasi --}}
                    @if ($patrol->started_at)
                        @php($duration = $patrol->durationSeconds())
                        <div class="flex flex-col gap-1 rounded-xl bg-slate-50 px-4 py-3">
                            <span class="flex items-center gap-1.5 text-xs font-medium text-slate-400">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                Durasi
                            </span>
                            <span class="text-sm font-semibold text-slate-700"
                                  data-patrol-duration
                                  data-seconds="{{ $duration }}"
                                  data-running="{{ $patrol->status === \App\Enums\PatrolStatus::InProgress ? 'true' : 'false' }}">{{ sprintf('%02d:%02d:%02d', intdiv($duration, 3600), intdiv($duration % 3600, 60), $duration % 60) }}</span>
                        </div>
                    @endif
                </div>

                {{-- GPS indicator (only when in-progress as ranger) --}}
                @if ($isInProgress && auth()->user()->isRanger())
                    <div class="mt-4">
                        <div data-gps-indicator class="inline-flex items-center gap-2 rounded-full border border-amber-200 bg-amber-50 px-3.5 py-1.5 text-xs font-medium text-amber-700">
                            <span class="inline-block h-2 w-2 animate-pulse rounded-full bg-amber-500"></span>
                            <span>Menghubungkan GPS...</span>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- ===== MAP CARD ===== --}}
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <div class="flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-forest-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                    </svg>
                    <h2 class="font-semibold text-slate-800">Peta Rute Patroli</h2>
                </div>
                @if ($isInProgress)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">
                        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"></span>
                        Tracking Aktif
                    </span>
                @endif
            </div>
            <div id="patrol-map" class="h-80 w-full bg-slate-100"
                 data-patrol-tracks-url="{{ route(auth()->user()->isAdmin() ? 'admin.patrols.tracks.index' : 'ranger.patrols.tracks.index', $patrol) }}"
                 data-record-track-url="{{ auth()->user()->isRanger() ? route('ranger.patrols.tracks.store', $patrol) : '' }}">
            </div>
        </div>

        {{-- ===== CHECKPOINTS CARD ===== --}}
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-100 px-5 py-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-forest-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                </svg>
                <h2 class="font-semibold text-slate-800">Rencana Checkpoint</h2>
                @php($totalCheckpoints = $patrol->patrolCheckpoints->count())
                @php($visitedCheckpoints = $patrol->patrolCheckpoints->whereNotNull('visited_at')->count())
                <span class="ml-auto text-xs text-slate-400">{{ $visitedCheckpoints }}/{{ $totalCheckpoints }} dikunjungi</span>
            </div>
            <ol class="divide-y divide-slate-100">
                @foreach ($patrol->patrolCheckpoints as $planned)
                    <li class="flex items-center gap-4 px-5 py-3.5">
                        {{-- Sequence number --}}
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold {{ $planned->visited_at ? 'bg-forest-700 text-white' : 'bg-slate-100 text-slate-400' }}">
                            {{ $planned->sequence }}
                        </span>
                        {{-- Checkpoint info --}}
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-slate-800">{{ $planned->checkpoint->name }}</p>
                            <p class="text-xs text-slate-400">{{ $planned->checkpoint->code }}</p>
                        </div>
                        {{-- Visited status --}}
                        @if ($planned->visited_at)
                            <span class="shrink-0 text-xs font-medium text-emerald-600">
                                <svg xmlns="http://www.w3.org/2000/svg" class="inline h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                Dikunjungi
                            </span>
                        @else
                            <span class="shrink-0 text-xs text-slate-400">Belum</span>
                        @endif
                    </li>
                @endforeach
            </ol>
        </div>

        {{-- ===== ACTION CARD (ranger only) ===== --}}
        @if (auth()->user()->isRanger())
            <div data-patrol-execution
                 data-status="{{ $patrol->status->value }}"
                 data-state-url="{{ route('ranger.patrols.show', $patrol) }}"
                 class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center gap-2 border-b border-slate-100 px-5 py-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-forest-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    <h2 class="font-semibold text-slate-800">Tindakan Patroli</h2>
                </div>

                <div class="p-5">
                    <p data-execution-message role="alert" class="mb-3 text-sm text-red-700"></p>
                    <a data-state-refresh href="{{ route('ranger.patrols.show', $patrol) }}"
                       class="mb-3 inline-flex items-center gap-1.5 text-sm text-forest-700 underline underline-offset-2" hidden>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Muat ulang kondisi terbaru
                    </a>

                    @if ($patrol->status === \App\Enums\PatrolStatus::Assigned)
                        @if ($activePatrol ?? null)
                            <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 text-sm text-amber-800">
                                <p class="font-medium">Selesaikan patroli yang sedang berlangsung sebelum memulai tugas lain.</p>
                                <a class="mt-2 inline-flex items-center gap-1.5 font-semibold text-forest-700 underline underline-offset-2"
                                   href="{{ route('ranger.patrols.show', $activePatrol) }}">
                                    Lanjutkan {{ $activePatrol->code }} →
                                </a>
                            </div>
                        @else
                            <form method="POST" action="{{ route('ranger.patrols.start', $patrol) }}" data-patrol-mutation>
                                @csrf
                                <x-button class="gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                                    Mulai Patroli
                                </x-button>
                            </form>
                        @endif

                    @elseif ($isInProgress)
                        <div class="space-y-3">
                            <x-button type="button" data-finish-open class="gap-2 border border-red-200 !bg-red-50 !text-red-700 hover:!bg-red-100">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/></svg>
                                Akhiri Patroli
                            </x-button>
                        </div>

                        <dialog data-finish-dialog aria-labelledby="finish-title"
                                class="m-auto w-[calc(100%-2rem)] max-w-md rounded-2xl p-6 shadow-xl backdrop:bg-slate-900/50">
                            <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-red-100">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            </div>
                            <h2 id="finish-title" class="text-lg font-bold text-slate-900">Akhiri patroli ini?</h2>
                            @php($unvisitedCount = $patrol->patrolCheckpoints->whereNull('visited_at')->count())
                            <p class="my-2 text-sm text-slate-500">
                                Masih ada <strong class="text-slate-700">{{ $unvisitedCount }} checkpoint</strong> belum dikunjungi. Patroli tetap dapat diakhiri.
                            </p>
                            <form method="POST" action="{{ route('ranger.patrols.finish', $patrol) }}" data-patrol-mutation class="mt-4 space-y-2">
                                @csrf
                                <x-button class="gap-2 !bg-red-600 hover:!bg-red-700">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/></svg>
                                    Ya, Akhiri Patroli
                                </x-button>
                                <x-button type="button" data-finish-cancel class="!bg-slate-100 !text-slate-700 hover:!bg-slate-200">Batal</x-button>
                            </form>
                        </dialog>
                        <noscript><p class="text-sm text-slate-500">Aktifkan JavaScript untuk membuka konfirmasi akhir patroli.</p></noscript>
                    @endif
                </div>
            </div>
        @endif

    </div>
@endsection
