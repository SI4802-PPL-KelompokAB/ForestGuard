@extends('layouts.app')

@section('title', 'Uji Coba Uploader Bukti Foto (PBI 9)')

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    {{-- Banner Pemberitahuan Lingkup Sandbox PBI 9 --}}
    <x-alert type="warning">
        <div class="flex items-start gap-2">
            <svg class="h-5 w-5 shrink-0 text-amber-600 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div>
                <p class="font-semibold">Halaman Uji Coba PBI 9 (Sandbox Unggah Bukti Foto)</p>
                <p class="text-xs text-amber-900 mt-0.5">
                    Halaman ini digunakan untuk memverifikasi fitur kompresi, ekstraksi EXIF, dan penyimpanan privat.
                    <em>Halaman ini akan dinonaktifkan setelah integrasi formulir pelaporan insiden PBI 10 selesai.</em>
                </p>
            </div>
        </div>
    </x-alert>

    {{-- Kartu Hasil Pengunggahan & Kompresi --}}
    @if (session('evidence_result'))
        @php
            $result = session('evidence_result');
            $origKb = round($result['original_size'] / 1024, 1);
            $compKb = round($result['compressed_size'] / 1024, 1);
        @endphp
        <x-card class="border-emerald-200 bg-emerald-50/40">
            <div class="flex items-center gap-2 border-b border-emerald-200 pb-3">
                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 text-emerald-800">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <h2 class="text-base font-semibold text-emerald-950">Bukti Foto Berhasil Tersimpan di Storage Privat</h2>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-4 text-xs md:grid-cols-2">
                <div class="rounded-xl border border-emerald-100 bg-white p-3 space-y-1.5 shadow-2xs">
                    <p class="font-semibold text-slate-500 uppercase tracking-wider text-[10px]">Optimasi Kompresi</p>
                    <p class="text-slate-800 font-medium">
                        Ukuran Asli: <span class="font-semibold">{{ $origKb }} KB</span>
                    </p>
                    <p class="text-emerald-700 font-medium">
                        Ukuran Terkompresi: <span class="font-bold">{{ $compKb }} KB</span>
                    </p>
                    <p class="text-slate-600">
                        Penghematan Ruang: <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-semibold bg-emerald-100 text-emerald-800">{{ $result['saved_percent'] }}%</span>
                    </p>
                </div>

                <div class="rounded-xl border border-emerald-100 bg-white p-3 space-y-1.5 shadow-2xs">
                    <p class="font-semibold text-slate-500 uppercase tracking-wider text-[10px]">Metadata Tersimpan</p>
                    <p class="text-slate-800">
                        Induk Laporan: <span class="font-semibold">{{ $result['parent_code'] }}</span>
                    </p>
                    <p class="text-slate-800">
                        Koordinat GPS: <span class="font-mono text-emerald-800">{{ $result['latitude'] }}, {{ $result['longitude'] }}</span>
                    </p>
                    <p class="text-slate-800">
                        Waktu Pengambilan: <span class="font-medium">{{ $result['captured_at'] ?? 'Tidak ada EXIF waktu' }}</span>
                    </p>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-emerald-100 pt-3">
                <p class="text-[11px] text-slate-500 font-mono truncate max-w-md">Path: {{ $result['file_path'] }}</p>
                <a
                    href="{{ $result['access_url'] }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-700 px-3.5 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-1"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                    </svg>
                    Buka Berkas Bukti (Signed URL 5 Menit)
                </a>
            </div>
        </x-card>
    @endif

    {{-- Formulir Pengujian Uploader --}}
    <x-card>
        <div class="border-b border-slate-200 pb-4">
            <h1 class="text-lg font-semibold text-forest-900">Form Uji Coba Unggah Bukti Foto Lapangan</h1>
            <p class="text-xs text-slate-600 mt-1">
                Pilih salah satu laporan insiden yang ada di database sebagai entitas induk (Parent), lalu unggah berkas foto.
            </p>
        </div>

        @if ($incidents->isEmpty())
            <div class="py-8 text-center">
                <p class="text-sm text-slate-600">Belum ada data laporan insiden di database untuk dijadikan entitas induk.</p>
                <p class="text-xs text-slate-500 mt-1">Silakan jalankan database seeder terlebih dahulu.</p>
            </div>
        @else
            <form method="POST" action="{{ route('evidence.test.store') }}" enctype="multipart/form-data" class="mt-5 space-y-5">
                @csrf

                <input type="hidden" name="parent_type" value="incident">

                {{-- Pemilihan Entitas Induk (Insiden yang Sudah Ada) --}}
                <div>
                    <label for="parent_id" class="mb-1 block text-sm font-medium text-slate-700">
                        Pilih Laporan Insiden Induk <span class="text-red-500">*</span>
                    </label>
                    <select
                        id="parent_id"
                        name="parent_id"
                        class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-forest-600 focus:outline-none focus:ring-2 focus:ring-forest-100"
                        required
                    >
                        <option value="">-- Pilih Insiden Terdaftar --</option>
                        @foreach ($incidents as $inc)
                            <option value="{{ $inc->id }}" {{ old('parent_id') == $inc->id ? 'selected' : '' }}>
                                {{ $inc->code }} &bull; {{ $inc->incidentType?->name ?? 'Insiden' }} &bull; Kawasan: {{ $inc->forestArea?->name ?? '-' }} (Pelapor: {{ $inc->reporter?->name ?? 'Anonim' }})
                            </option>
                        @endforeach
                    </select>
                    @error('parent_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Komponen Reusable Blade Uploader PBI 9 --}}
                <x-file-uploader
                    name="photo"
                    label="Pilih atau Ambil Foto Lapangan"
                />

                {{-- Tombol Submit --}}
                <div class="pt-2">
                    <x-button type="submit">
                        <svg class="h-4 w-4 mr-2 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                        Unggah Bukti Lapangan
                    </x-button>
                </div>
            </form>
        @endif
    </x-card>
</div>
@endsection

