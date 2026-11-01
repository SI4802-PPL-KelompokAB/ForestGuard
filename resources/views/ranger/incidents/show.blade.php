@extends('layouts.app')

@section('title', 'Detail Insiden ' . $incident->code)

@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-bold text-forest-950">{{ $incident->code }}</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-forest-100 text-forest-800">
                    {{ $incident->caseStatus?->name }}
                </span>
            </div>
            <p class="text-sm text-slate-500">Kawasan: {{ $incident->forestArea?->name }} · Waktu Lapor: {{ $incident->created_at?->format('d M Y, H:i') }} WIB</p>
        </div>
        <a href="{{ route('ranger.home') }}" class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50">
            Kembali ke Beranda
        </a>
    </div>

    @if (session('status'))
        <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    {{-- Indikator Progres Status --}}
    <x-card>
        <h2 class="text-sm font-semibold text-slate-700 mb-3 uppercase tracking-wider">Tahapan Kasus</h2>
        <x-incident-progress :incident="$incident" />
    </x-card>

    {{-- Aksi Cepat Ranger jika status Assigned --}}
    @if ($incident->caseStatus?->name === 'Assigned')
        <div class="p-4 bg-orange-50 border border-orange-200 rounded-xl flex items-center justify-between">
            <div>
                <p class="text-sm font-bold text-orange-900">Kasus Ditugaskan Kepada Anda</p>
                <p class="text-xs text-orange-700">Mulai tindakan tanggap darurat atau verifikasi di lapangan.</p>
            </div>
            <form method="POST" action="{{ route('ranger.cases.update-status', $incident) }}">
                @csrf
                <input type="hidden" name="to_status" value="Investigation/Response">
                <button type="submit" class="px-4 py-2 bg-orange-600 text-white font-semibold text-xs rounded-lg hover:bg-orange-700 shadow-sm transition">
                    Mulai Investigasi Lapangan
                </button>
            </form>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="md:col-span-2 space-y-6">
            <x-card>
                <h2 class="text-base font-bold text-slate-900 mb-4">Informasi Temuan</h2>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
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
                        <dd class="mt-1 font-mono text-xs text-slate-800">{{ $incident->latitude }}, {{ $incident->longitude }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs text-slate-500 uppercase tracking-wider">Uraian Kejadian</dt>
                        <dd class="mt-1 text-slate-700 bg-slate-50 p-3 rounded-lg border border-slate-100 whitespace-pre-line">{{ $incident->description }}</dd>
                    </div>
                </dl>
            </x-card>

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

        <div>
            <x-card>
                <h2 class="text-base font-bold text-slate-900 mb-4">Lini Masa Status</h2>
                <x-incident-timeline :histories="$incident->statusHistories->sortByDesc('changed_at')" />
            </x-card>
        </div>
    </div>
</div>
@endsection

