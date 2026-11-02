@extends('layouts.app')

@section('title', 'Detail Laporan ' . $incident->code)

@section('content')
<div class="mx-auto max-w-5xl space-y-5">
    <a href="{{ route('citizen.home') }}"
       class="inline-flex items-center gap-2 text-sm font-semibold text-forest-800 hover:text-forest-600">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 19-7-7 7-7" />
        </svg>
        Kembali ke Laporan Saya
    </a>

    @php
        $statusName = $incident->caseStatus?->name ?? 'Unknown';
        $badgeClasses = match ($statusName) {
            'Rejected' => 'bg-red-100 text-red-800',
            'Resolved' => 'bg-emerald-100 text-emerald-800',
            default => 'bg-orange-100 text-orange-800',
        };
    @endphp

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="font-mono text-sm font-semibold text-forest-800">{{ $incident->code }}</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-950">{{ $incident->incidentType?->name ?? 'Laporan insiden' }}</h1>
                <p class="mt-1 text-sm text-slate-600">{{ $incident->forestArea?->name }}</p>
            </div>
            <span class="inline-flex items-center gap-2 self-start rounded-full px-3 py-1.5 text-sm font-semibold {{ $badgeClasses }}">
                <span class="h-2 w-2 rounded-full bg-current"></span>
                {{ $statusName }}
            </span>
        </div>
    </section>

    @if (session('incident_code'))
        <section class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 sm:p-5" role="status">
            <h2 class="font-bold text-emerald-900">Laporan berhasil terkirim</h2>
            <p class="mt-1 text-sm leading-6 text-emerald-800">
                Laporan Anda tercatat dengan kode {{ session('incident_code') }} dan akan diperiksa oleh tim pengelola kawasan hutan.
            </p>
        </section>
    @endif

    @if ($isRejected)
        <section class="rounded-xl border border-red-200 bg-red-50 p-4 sm:p-5" role="alert">
            <h2 class="font-bold text-red-900">Laporan ditolak</h2>
            <p class="mt-1 whitespace-pre-line text-sm leading-6 text-red-800">
                {{ $incident->reject_reason ?: 'Mohon maaf, laporan ini belum dapat ditindaklanjuti.' }}
            </p>
        </section>
    @endif

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
        <div class="space-y-5 lg:col-span-2">
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-bold text-slate-900">Informasi laporan</h2>
                <dl class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Jenis insiden</dt>
                        <dd class="mt-1 text-sm font-medium text-slate-900">{{ $incident->incidentType?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Kawasan hutan</dt>
                        <dd class="mt-1 text-sm font-medium text-slate-900">{{ $incident->forestArea?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Waktu kejadian</dt>
                        <dd class="mt-1 text-sm text-slate-800">
                            {{ $incident->occurred_at?->timezone('Asia/Jakarta')->translatedFormat('j M Y, H:i') ?? 'Tidak dicantumkan' }} WIB
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Laporan dikirim</dt>
                        <dd class="mt-1 text-sm text-slate-800">
                            {{ $incident->created_at?->timezone('Asia/Jakarta')->translatedFormat('j M Y, H:i') ?? '—' }} WIB
                        </dd>
                    </div>
                    @if ($incident->latitude !== null && $incident->longitude !== null)
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Koordinat lokasi</dt>
                            <dd class="mt-1 font-mono text-sm text-slate-800">{{ $incident->latitude }}, {{ $incident->longitude }}</dd>
                        </div>
                    @endif
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Deskripsi</dt>
                        <dd class="mt-2 whitespace-pre-line rounded-lg bg-slate-50 p-3 text-sm leading-6 text-slate-700">
                            {{ $incident->description }}
                        </dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Terakhir diperbarui</dt>
                        <dd class="mt-1 text-sm text-slate-800">
                            {{ $incident->updated_at?->timezone('Asia/Jakarta')->translatedFormat('j M Y, H:i') ?? '—' }} WIB
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-bold text-slate-900">Foto bukti</h2>
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    @forelse ($incident->evidences as $evidence)
                        <figure class="overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
                            <img src="{{ route('evidence.show', $evidence->id) }}"
                                 alt="Foto bukti {{ $incident->code }}"
                                 class="h-56 w-full object-cover"
                                 loading="lazy">
                            @if ($evidence->captured_at)
                                <figcaption class="p-3 text-xs text-slate-500">
                                    Diambil {{ $evidence->captured_at->timezone('Asia/Jakarta')->translatedFormat('j M Y, H:i') }} WIB
                                </figcaption>
                            @endif
                        </figure>
                    @empty
                        <p class="text-sm text-slate-600">Belum ada foto bukti untuk laporan ini.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <section class="h-fit rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900">Tahapan penanganan</h2>
            <ol class="mt-5">
                @forelse ($timeline as $step)
                    @php
                        $dotClasses = match ($step['state']) {
                            'complete' => 'bg-forest-700 text-white',
                            'current' => $isRejected ? 'bg-red-600 text-white' : 'bg-orange-500 text-white',
                            default => 'bg-slate-200 text-slate-500',
                        };
                        $labelClasses = match ($step['state']) {
                            'complete' => 'font-medium text-slate-800',
                            'current' => $isRejected ? 'font-bold text-red-800' : 'font-bold text-forest-900',
                            default => 'text-slate-500',
                        };
                    @endphp
                    <li class="relative flex gap-3 {{ $loop->last ? '' : 'pb-6' }}">
                        @if (! $loop->last)
                            <span class="absolute left-3 top-7 h-full w-px bg-slate-200" aria-hidden="true"></span>
                        @endif
                        <span class="relative z-10 flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold {{ $dotClasses }}">
                            @if ($step['state'] === 'complete')
                                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.25 7.31a1 1 0 0 1-1.42.003L3.29 9.293a1 1 0 1 1 1.42-1.407l4.04 4.08 6.54-6.67a1 1 0 0 1 1.414-.006Z" clip-rule="evenodd" />
                                </svg>
                            @else
                                {{ $loop->iteration }}
                            @endif
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm {{ $labelClasses }}">{{ $step['name'] }}</p>
                            @if ($step['changedAt'])
                                <p class="mt-0.5 text-xs text-slate-500">
                                    {{ $step['changedAt']->timezone('Asia/Jakarta')->translatedFormat('j M Y, H:i') }} WIB
                                </p>
                            @endif
                            @if ($step['state'] === 'current')
                                <p class="mt-1 text-xs font-medium {{ $isRejected ? 'text-red-700' : 'text-forest-700' }}">Status saat ini</p>
                            @endif
                        </div>
                    </li>
                @empty
                    <li class="text-sm text-slate-600">Tahapan status belum tersedia.</li>
                @endforelse
            </ol>
        </section>
    </div>
</div>
@endsection
