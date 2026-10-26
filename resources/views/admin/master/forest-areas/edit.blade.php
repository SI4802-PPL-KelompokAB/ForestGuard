@extends('layouts.app')

@section('title', 'Ubah Kawasan Hutan')

@section('content')
    <div class="mb-5">
        <a href="{{ route('admin.master.forest-areas.index') }}" class="text-sm text-forest-700 hover:underline">Kembali ke daftar kawasan</a>
        <h1 class="mt-2 text-xl font-semibold text-forest-900">Ubah Kawasan Hutan</h1>
        <p class="mt-1 text-sm text-slate-600">Perbarui data wilayah konservasi.</p>
    </div>

    <x-card class="max-w-2xl">
        <form method="POST" action="{{ route('admin.master.forest-areas.update', $forestArea) }}" class="space-y-4">
            @csrf
            @method('PUT')
            <x-input name="name" label="Nama kawasan" :value="$forestArea->name" required />

            <div>
                <label for="description" class="mb-1 block text-sm font-medium text-slate-700">Deskripsi (Opsional)</label>
                <textarea id="description" name="description" rows="3"
                          class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-forest-600 focus:outline-none focus:ring-2 focus:ring-forest-100">{{ old('description', $forestArea->description) }}</textarea>
                @error('description')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <x-input name="area_ha" type="number" step="0.01" label="Luas kawasan (hektar)" :value="$forestArea->area_ha" required />

            <div class="grid gap-4 sm:grid-cols-2">
                <x-input name="center_lat" type="number" step="0.0000001" label="Latitude Pusat" :value="$forestArea->center_lat" required />
                <x-input name="center_lng" type="number" step="0.0000001" label="Longitude Pusat" :value="$forestArea->center_lng" required />
            </div>

            <div>
                <label for="boundary" class="mb-1 block text-sm font-medium text-slate-700">Batas Kawasan (GeoJSON Polygon Opsional)</label>
                <textarea id="boundary" name="boundary" rows="4"
                          class="font-mono text-xs block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 shadow-sm focus:border-forest-600 focus:outline-none focus:ring-2 focus:ring-forest-100">{{ old('boundary', $forestArea->boundary ? json_encode($forestArea->boundary, JSON_PRETTY_PRINT) : '') }}</textarea>
                @error('boundary')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs text-slate-500">Kosongkan jika belum memiliki data boundary GeoJSON.</p>
            </div>

            <x-button>Simpan perubahan</x-button>
        </form>
    </x-card>
@endsection
