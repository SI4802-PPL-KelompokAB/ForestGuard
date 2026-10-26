@extends('layouts.app')

@section('title', 'Tambah Checkpoint')

@section('content')
    <div class="mb-5">
        <a href="{{ route('admin.master.checkpoints.index') }}" class="text-sm text-forest-700 hover:underline">Kembali ke daftar checkpoint</a>
        <h1 class="mt-2 text-xl font-semibold text-forest-900">Tambah Checkpoint</h1>
        <p class="mt-1 text-sm text-slate-600">Daftarkan titik pos pantau baru di kawasan hutan.</p>
    </div>

    <x-card class="max-w-2xl">
        <form method="POST" action="{{ route('admin.master.checkpoints.store') }}" class="space-y-4">
            @csrf
            <div>
                <label for="forest_area_id" class="mb-1 block text-sm font-medium text-slate-700">Kawasan Hutan</label>
                <select id="forest_area_id" name="forest_area_id" required
                        class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-forest-600 focus:outline-none focus:ring-2 focus:ring-forest-100">
                    <option value="">Pilih kawasan hutan</option>
                    @foreach ($forestAreas as $area)
                        <option value="{{ $area->id }}" @selected(old('forest_area_id') == $area->id)>{{ $area->name }}</option>
                    @endforeach
                </select>
                @error('forest_area_id')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <x-input name="code" label="Kode checkpoint" required placeholder="Contoh: CP-BKB-01" />
            <x-input name="name" label="Nama checkpoint" required placeholder="Contoh: Pos Pantau Lembah Hijau" />

            <div class="grid gap-4 sm:grid-cols-2">
                <x-input name="latitude" type="number" step="0.0000001" label="Latitude" required placeholder="Contoh: -6.1234567" />
                <x-input name="longitude" type="number" step="0.0000001" label="Longitude" required placeholder="Contoh: 106.1234567" />
            </div>

            <x-button>Simpan checkpoint</x-button>
        </form>
    </x-card>
@endsection
