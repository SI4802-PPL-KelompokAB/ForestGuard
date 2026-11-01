@extends('layouts.app')

@section('title', 'Kelola Kasus ' . $incident->code)

@section('content')
<div class="mx-auto max-w-5xl space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-forest-950">{{ $incident->code }}</h1>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-800">
                    Status: {{ $incident->caseStatus?->name }}
                </span>
                @if ($incident->riskLevel)
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">
                        Risiko: {{ $incident->riskLevel->name }}
                    </span>
                @endif
            </div>
            <p class="text-sm text-slate-500 mt-1">
                Kawasan: <strong>{{ $incident->forestArea?->name }}</strong> · Pelapor: {{ $incident->reporter?->name ?? 'Anonim' }} ({{ $incident->reporter?->email }})
            </p>
        </div>
        <a href="{{ route('admin.incidents.index') }}" class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50">
            Kembali ke Daftar Kasus
        </a>
    </div>

    @if (session('status'))
        <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-lg bg-red-50 p-4 border border-red-200">
            <h2 class="text-sm font-semibold text-red-800">Gagal memperbarui status:</h2>
            <ul class="mt-2 list-disc list-inside text-xs text-red-700 space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Indikator Progres Status --}}
    <x-card>
        <h2 class="text-sm font-semibold text-slate-700 mb-3 uppercase tracking-wider">Tahapan Kasus</h2>
        <x-incident-progress :incident="$incident" />
    </x-card>

    {{-- KONTROL AKSI PERUBAHAN STATUS (STATE MACHINE) --}}
    @php
        $currentStatusName = $incident->caseStatus?->name;
    @endphp

    @if ($currentStatusName === 'Reported')
        <x-card class="bg-amber-50 border-amber-200">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-amber-900">Kasus Baru Membutuhkan Verifikasi</h3>
                    <p class="text-xs text-amber-700">Mulai proses peninjauan bukti dan lokasi untuk memeriksa kebenaran laporan.</p>
                </div>
                <form method="POST" action="{{ route('admin.incidents.update-status', $incident) }}">
                    @csrf
                    <input type="hidden" name="to_status" value="Under Verification">
                    <button type="submit" class="px-4 py-2 bg-amber-600 text-white font-semibold text-xs rounded-lg hover:bg-amber-700 shadow-sm transition">
                        Mulai Verifikasi Kasus
                    </button>
                </form>
            </div>
        </x-card>
    @elseif ($currentStatusName === 'Under Verification')
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            {{-- Form Terverifikasi --}}
            <x-card class="border-blue-200 bg-blue-50/50">
                <h3 class="text-sm font-bold text-blue-950 mb-2">1. Tetapkan Hasil: Terverifikasi (Verified)</h3>
                <p class="text-xs text-slate-600 mb-3">Tentukan tingkat risiko untuk melanjutkan kasus ke tahap penugasan.</p>
                <form method="POST" action="{{ route('admin.incidents.update-status', $incident) }}" class="space-y-3">
                    @csrf
                    <input type="hidden" name="to_status" value="Verified">
                    <div>
                        <label for="risk_level_id" class="block text-xs font-semibold text-slate-700 mb-1">Tingkat Risiko <span class="text-red-500">*</span></label>
                        <select name="risk_level_id" id="risk_level_id" class="w-full text-xs rounded-lg border-slate-300" required>
                            <option value="">-- Pilih Tingkat Risiko --</option>
                            @foreach ($riskLevels as $rl)
                                <option value="{{ $rl->id }}" {{ old('risk_level_id') == $rl->id ? 'selected' : '' }}>
                                    {{ $rl->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="note" class="block text-xs font-semibold text-slate-700 mb-1">Catatan Verifikasi</label>
                        <input type="text" name="note" id="note" placeholder="Catatan opsional..." class="w-full text-xs rounded-lg border-slate-300">
                    </div>
                    <button type="submit" class="w-full py-2 bg-blue-600 text-white font-semibold text-xs rounded-lg hover:bg-blue-700 transition">
                        Verifikasi Laporan
                    </button>
                </form>
            </x-card>

            {{-- Form Tolak Laporan --}}
            <x-card class="border-red-200 bg-red-50/50">
                <h3 class="text-sm font-bold text-red-950 mb-2">2. Tetapkan Hasil: Tolak Laporan (Rejected)</h3>
                <p class="text-xs text-slate-600 mb-3">Jika laporan tidak terbukti atau fiktif, tolak laporan dengan menyertakan alasan.</p>
                <form method="POST" action="{{ route('admin.incidents.update-status', $incident) }}" class="space-y-3">
                    @csrf
                    <input type="hidden" name="to_status" value="Rejected">
                    <div>
                        <label for="reject_reason" class="block text-xs font-semibold text-slate-700 mb-1">Alasan Penolakan <span class="text-red-500">*</span> (min 10 karakter)</label>
                        <textarea name="reject_reason" id="reject_reason" rows="3" placeholder="Jelaskan alasan penolakan secara jelas..." class="w-full text-xs rounded-lg border-slate-300" required>{{ old('reject_reason') }}</textarea>
                    </div>
                    <button type="submit" class="w-full py-2 bg-red-600 text-white font-semibold text-xs rounded-lg hover:bg-red-700 transition">
                        Tolak Laporan Ini
                    </button>
                </form>
            </x-card>
        </div>
    @elseif ($currentStatusName === 'Investigation/Response')
        <x-card class="bg-purple-50 border-purple-200">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-purple-900">Tahap Investigasi Lapangan Selesai?</h3>
                    <p class="text-xs text-purple-700">Pindahkan status ke tahap Monitoring untuk pemantauan hasil penanganan.</p>
                </div>
                <form method="POST" action="{{ route('admin.incidents.update-status', $incident) }}">
                    @csrf
                    <input type="hidden" name="to_status" value="Monitoring">
                    <button type="submit" class="px-4 py-2 bg-purple-600 text-white font-semibold text-xs rounded-lg hover:bg-purple-700 transition">
                        Pindahkan ke Monitoring
                    </button>
                </form>
            </div>
        </x-card>
    @elseif ($currentStatusName === 'Monitoring')
        <x-card class="bg-emerald-50 border-emerald-200">
            <h3 class="text-sm font-bold text-emerald-950 mb-2">Tutup & Selesaikan Kasus (Resolved)</h3>
            <p class="text-xs text-slate-600 mb-3">Tutup kasus ini jika ancaman telah tertangani dan masa pemantauan telah selesai.</p>
            <form method="POST" action="{{ route('admin.incidents.update-status', $incident) }}" class="space-y-3">
                @csrf
                <input type="hidden" name="to_status" value="Resolved">
                <div>
                    <label for="resolution_note" class="block text-xs font-semibold text-slate-700 mb-1">Catatan Penyelesaian Kasus <span class="text-red-500">*</span> (min 10 karakter)</label>
                    <textarea name="resolution_note" id="resolution_note" rows="3" placeholder="Tuliskan ringkasan hasil penyelesaian kasus..." class="w-full text-xs rounded-lg border-slate-300" required>{{ old('resolution_note') }}</textarea>
                </div>
                <button type="submit" class="px-6 py-2 bg-emerald-700 text-white font-semibold text-xs rounded-lg hover:bg-emerald-800 transition">
                    Selesaikan dan Tutup Kasus
                </button>
            </form>
        </x-card>
    @endif

    {{-- Detail & Timeline --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="md:col-span-2 space-y-6">
            <x-card>
                <h2 class="text-base font-bold text-slate-900 mb-4">Informasi Kasus</h2>
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
                    @if ($incident->reject_reason)
                        <div class="sm:col-span-2 p-3 bg-red-50 border border-red-200 rounded-lg">
                            <dt class="text-xs font-bold text-red-800 uppercase tracking-wider">Alasan Penolakan</dt>
                            <dd class="mt-1 text-sm text-red-700">{{ $incident->reject_reason }}</dd>
                        </div>
                    @endif
                    @if ($incident->resolution_note)
                        <div class="sm:col-span-2 p-3 bg-emerald-50 border border-emerald-200 rounded-lg">
                            <dt class="text-xs font-bold text-emerald-800 uppercase tracking-wider">Catatan Penyelesaian</dt>
                            <dd class="mt-1 text-sm text-emerald-700">{{ $incident->resolution_note }}</dd>
                            <p class="text-xs text-emerald-600 mt-1">Ditutup pada: {{ $incident->resolved_at?->format('d M Y, H:i') }} WIB</p>
                        </div>
                    @endif
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
                <h2 class="text-base font-bold text-slate-900 mb-4">Lini Masa Riwayat Kasus</h2>
                <x-incident-timeline :histories="$incident->statusHistories->sortByDesc('changed_at')" />
            </x-card>
        </div>
    </div>
</div>
@endsection

