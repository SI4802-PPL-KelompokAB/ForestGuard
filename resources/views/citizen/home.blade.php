@extends('layouts.app')

@section('title', 'Laporan Saya')

@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <header>
        <h1 class="text-2xl font-bold text-forest-950 sm:text-3xl">Laporan saya</h1>
        <p class="text-sm text-slate-600">Pantau status laporan yang Anda kirim.</p>
    </header>

    <section class="flex flex-col gap-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:p-6">
        <div>
            <h2 class="text-xl font-bold text-slate-900">Halo, {{ $firstName }}</h2>
            <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-600">
                Laporkan dugaan ancaman hutan di sekitar Anda. Setiap laporan diperiksa Admin sebelum ditindaklanjuti.
            </p>
        </div>
        @if ($canCreateIncident)
            <a href="{{ route('citizen.incidents.create') }}"
               class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg bg-orange-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14m7-7H5" />
                </svg>
                Buat laporan
            </a>
        @endif
    </section>

    <section class="grid grid-cols-1 gap-3 sm:grid-cols-3 sm:gap-4" aria-label="Ringkasan laporan">
        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-3xl font-bold text-forest-950">{{ $totalReports }}</p>
            <h2 class="mt-1 text-sm text-slate-600">Laporan terkirim</h2>
        </article>
        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-3xl font-bold text-forest-950">{{ $processingReports }}</p>
            <h2 class="mt-1 text-sm text-slate-600">Sedang diproses</h2>
        </article>
        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-3xl font-bold text-forest-950">{{ $resolvedReports }}</p>
            <h2 class="mt-1 text-sm text-slate-600">Selesai ditangani</h2>
        </article>
    </section>

    <section class="space-y-3">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-bold text-slate-900">Riwayat laporan</h2>
            <span class="text-sm text-slate-500">{{ $incidents->total() }} laporan</span>
        </div>

        <div class="space-y-3">
            @forelse ($incidents as $report)
                @php
                    $incident = $report['incident'];
                    $badgeClasses = match ($report['currentStatus']) {
                        'Rejected' => 'bg-red-100 text-red-800',
                        'Resolved' => 'bg-emerald-100 text-emerald-800',
                        default => 'bg-orange-100 text-orange-800',
                    };
                @endphp
                <a href="{{ route('citizen.incidents.show', $incident) }}"
                   class="block rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-forest-600 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-forest-600 sm:p-5">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="flex min-w-0 gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-forest-700 text-sm font-bold text-white">
                                {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($incident->incidentType?->name ?? 'L', 0, 2)) }}
                            </span>
                            <div class="min-w-0">
                                <h3 class="font-semibold text-slate-900">
                                    {{ $incident->incidentType?->name ?? 'Laporan insiden' }}
                                    @if ($incident->forestArea?->name)
                                        <span class="font-normal text-slate-500">· {{ $incident->forestArea->name }}</span>
                                    @endif
                                </h3>
                                <p class="mt-1 line-clamp-2 text-sm leading-5 text-slate-600">{{ \Illuminate\Support\Str::limit($incident->description, 160) }}</p>
                                <p class="mt-2 font-mono text-xs text-slate-500">
                                    {{ $incident->code }}
                                    <span class="px-1">·</span>
                                    {{ $incident->created_at?->timezone('Asia/Jakarta')->translatedFormat('j M Y, H:i') }}
                                </p>
                            </div>
                        </div>
                        <span class="inline-flex shrink-0 items-center gap-1.5 self-start rounded-full px-2.5 py-1 text-xs font-medium {{ $badgeClasses }}">
                            <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                            {{ $report['currentStatus'] }}
                        </span>
                    </div>

                    <div class="mt-4 space-y-2 border-t border-slate-100 pt-4">
                        <div class="grid gap-1" style="grid-template-columns: repeat({{ max(1, $report['steps']->count()) }}, minmax(0, 1fr))" role="img" aria-label="Progres status {{ $report['currentStatus'] }}">
                            @foreach ($report['steps'] as $step)
                                <span class="h-1.5 rounded-full {{ match ($step['state']) {
                                    'complete' => 'bg-forest-700',
                                    'current' => 'bg-forest-700',
                                    default => 'bg-slate-200',
                                } }}"></span>
                            @endforeach
                        </div>
                        <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
                            <span class="font-semibold text-slate-800">
                                {{ $report['isRejected'] ? 'Ditolak' : $report['currentStatus'] }}
                            </span>
                            @if ($report['nextStatus'])
                                <span class="text-slate-500">Berikutnya: {{ $report['nextStatus'] }}</span>
                            @elseif ($report['currentStatus'] === 'Resolved')
                                <span class="font-medium text-forest-700">Kasus selesai</span>
                            @endif
                        </div>
                    </div>
                </a>
            @empty
                <div class="rounded-xl border border-dashed border-slate-300 bg-white px-5 py-12 text-center">
                    <h3 class="font-semibold text-slate-900">Belum ada laporan</h3>
                    <p class="mt-1 text-sm text-slate-600">Laporan yang Anda kirim akan muncul di halaman ini.</p>
                    @if ($canCreateIncident)
                        <a href="{{ route('citizen.incidents.create') }}"
                           class="mt-4 inline-flex items-center justify-center rounded-lg bg-forest-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-forest-800">
                            Buat laporan pertama
                        </a>
                    @endif
                </div>
            @endforelse
        </div>

        @if ($incidents->hasPages())
            <div class="pt-2">
                {{ $incidents->links() }}
            </div>
        @endif
    </section>
</div>
@endsection
