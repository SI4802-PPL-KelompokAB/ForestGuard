@extends('layouts.app')

@section('title', 'Tambah Kawasan Hutan')

@section('content')
    <div class="mb-5">
        <a href="{{ route('admin.master.forest-areas.index') }}" class="text-sm text-forest-700 hover:underline">Kembali ke daftar kawasan</a>
        <h1 class="mt-2 text-xl font-semibold text-forest-900">Tambah Kawasan Hutan</h1>
        <p class="mt-1 text-sm text-slate-600">Daftarkan wilayah konservasi atau kawasan patroli baru.</p>
    </div>

    <x-card class="max-w-2xl">
        <form method="POST" action="{{ route('admin.master.forest-areas.store') }}" class="space-y-4">
            @csrf
            <x-input name="name" label="Nama kawasan" required placeholder="Contoh: Kawasan Konservasi Bukit Barisan" />

            <div>
                <label for="description" class="mb-1 block text-sm font-medium text-slate-700">Deskripsi (Opsional)</label>
                <textarea id="description" name="description" rows="3"
                          class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-forest-600 focus:outline-none focus:ring-2 focus:ring-forest-100"
                          placeholder="Keterangan singkat mengenai batas kawasan atau ekosistem">{{ old('description') }}</textarea>
                @error('description')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <x-input name="area_ha" type="number" step="0.01" label="Luas kawasan (hektar)" required placeholder="Contoh: 1540.50" />

            <div class="grid gap-4 sm:grid-cols-2">
                <x-input name="center_lat" type="number" step="0.0000001" label="Latitude Pusat" required placeholder="Contoh: -6.1234567" />
                <x-input name="center_lng" type="number" step="0.0000001" label="Longitude Pusat" required placeholder="Contoh: 106.1234567" />
            </div>

            <div>
                <label for="boundary" class="mb-1 block text-sm font-medium text-slate-700">Batas Kawasan (GeoJSON Polygon Opsional)</label>
                <textarea id="boundary" name="boundary" rows="4"
                          class="font-mono text-xs block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 shadow-sm focus:border-forest-600 focus:outline-none focus:ring-2 focus:ring-forest-100"
                          placeholder='{"type": "Polygon", "coordinates": [[[106.1, -6.1], [106.2, -6.1], ...]]}'>{{ old('boundary') }}</textarea>
                @error('boundary')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs text-slate-500">Kosongkan jika belum memiliki data boundary GeoJSON.</p>
            </div>

            <x-button>Simpan kawasan</x-button>
        </form>
    </x-card>
@endsection
