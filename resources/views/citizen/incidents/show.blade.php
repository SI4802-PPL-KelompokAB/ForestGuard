@extends('layouts.app')

@section('title', 'Detail Laporan ' . $incident->code)

@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    {{-- Header & Kode Tiket --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-bold text-forest-950">{{ $incident->code }}</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-800">
                    Dugaan (suspected)
                </span>
            </div>
            <p class="text-sm text-slate-500">Dilaporkan pada {{ $incident->created_at?->format('d M Y, H:i') }} WIB</p>
        </div>
        <a href="{{ route('citizen.home') }}" class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50">
            Kembali ke Laporan Saya
        </a>
    </div>

    {{-- Modal Konfirmasi Sukses (Muncul jika ada session incident_code) --}}
    @if (session('incident_code'))
        <div class="rounded-xl bg-emerald-50 p-5 border border-emerald-200">
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0">
                    <svg class="h-6 w-6 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-base font-bold text-emerald-900">Laporan Berhasil Terkirim!</h2>
                    <p class="mt-1 text-sm text-emerald-800">
                        Laporan Anda telah berhasil dicatat dengan kode tiket <span class="font-mono font-bold">{{ session('incident_code') }}</span>. Laporan ini berstatus awal <strong>Reported</strong> dan akan segera diverifikasi oleh tim pengelola kawasan hutan.
                    </p>
                </div>
            </div>
        </div>
    @endif

    {{-- Indikator Progres Status --}}
    <x-card>
        <h2 class="text-sm font-semibold text-slate-700 mb-3 uppercase tracking-wider">Tahapan Status Penanganan</h2>
        <x-incident-progress :incident="$incident" />
    </x-card>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        {{-- Detail Informasi Laporan --}}
        <div class="md:col-span-2 space-y-6">
            <x-card>
                <h2 class="text-base font-bold text-slate-900 mb-4">Informasi Kejadian</h2>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-4 text-sm">
                    <div>
                        <dt class="text-xs text-slate-500 uppercase tracking-wider">Jenis Insiden</dt>
                        <dd class="mt-1 font-semibold text-slate-800">{{ $incident->incidentType?->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500 uppercase tracking-wider">Kawasan Hutan</dt>
                        <dd class="mt-1 font-semibold text-slate-800">{{ $incident->forestArea?->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500 uppercase tracking-wider">Waktu Kejadian</dt>
                        <dd class="mt-1 text-slate-800">{{ $incident->occurred_at?->format('d M Y, H:i') }} WIB</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500 uppercase tracking-wider">Koordinat GPS</dt>
                        <dd class="mt-1 font-mono text-xs text-slate-800">
                            {{ $incident->latitude }}, {{ $incident->longitude }}
                            @if ($incident->gps_accuracy)
                                <span class="text-slate-500">(±{{ round($incident->gps_accuracy) }}m)</span>
                            @endif
                        </dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs text-slate-500 uppercase tracking-wider">Uraian / Rincian</dt>
                        <dd class="mt-1 text-slate-700 whitespace-pre-line bg-slate-50 p-3 rounded-lg border border-slate-100">
                            {{ $incident->description }}
                        </dd>
                    </div>
                    @if ($incident->reject_reason)
                        <div class="sm:col-span-2 p-3 bg-red-50 border border-red-200 rounded-lg">
                            <dt class="text-xs font-bold text-red-800 uppercase tracking-wider">Alasan Penolakan Laporan</dt>
                            <dd class="mt-1 text-sm text-red-700">{{ $incident->reject_reason }}</dd>
                        </div>
                    @endif
                    @if ($incident->resolution_note)
                        <div class="sm:col-span-2 p-3 bg-emerald-50 border border-emerald-200 rounded-lg">
                            <dt class="text-xs font-bold text-emerald-800 uppercase tracking-wider">Catatan Penyelesaian</dt>
                            <dd class="mt-1 text-sm text-emerald-700">{{ $incident->resolution_note }}</dd>
                        </div>
                    @endif
                </dl>
            </x-card>

            {{-- Foto Bukti --}}
            <x-card>
                <h2 class="text-base font-bold text-slate-900 mb-3">Foto Bukti</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @forelse ($incident->evidences as $evidence)
                        <div class="overflow-hidden rounded-lg border border-slate-200 bg-slate-100">
                            <img src="{{ asset('storage/' . $evidence->file_path) }}" alt="Bukti Foto" class="w-full h-48 object-cover">
                            <div class="p-2 text-xs text-slate-500 bg-white">
                                Diambil: {{ $evidence->captured_at?->format('d M Y, H:i') }} WIB
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Tidak ada berkas bukti foto.</p>
                    @endforelse
                </div>
            </x-card>
        </div>

        {{-- Lini Masa Riwayat Status (PBI-11) --}}
        <div>
            <x-card>
                <h2 class="text-base font-bold text-slate-900 mb-4">Riwayat Perkembangan</h2>
                <x-incident-timeline :histories="$incident->statusHistories->sortByDesc('changed_at')" />
            </x-card>
        </div>
    </div>
</div>
@endsection

