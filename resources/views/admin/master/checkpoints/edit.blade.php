@extends('layouts.app')

@section('title', 'Ubah Checkpoint')

@section('content')
    <div class="mb-5">
        <a href="{{ route('admin.master.checkpoints.index') }}" class="text-sm text-forest-700 hover:underline">Kembali ke daftar checkpoint</a>
        <h1 class="mt-2 text-xl font-semibold text-forest-900">Ubah Checkpoint</h1>
        <p class="mt-1 text-sm text-slate-600">Perbarui data pos pantau.</p>
    </div>

    <x-card class="max-w-2xl">
        <form method="POST" action="{{ route('admin.master.checkpoints.update', $checkpoint) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label for="forest_area_id" class="mb-1 block text-sm font-medium text-slate-700">Kawasan Hutan</label>
                <select id="forest_area_id" name="forest_area_id" required
                        class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-forest-600 focus:outline-none focus:ring-2 focus:ring-forest-100">
                    @foreach ($forestAreas as $area)
                        <option value="{{ $area->id }}" @selected(old('forest_area_id', $checkpoint->forest_area_id) == $area->id)>
                            {{ $area->name }} {{ $area->trashed() ? '(Kawasan Terhapus)' : '' }}
                        </option>
                    @endforeach
                </select>
                @error('forest_area_id')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <x-input name="code" label="Kode checkpoint" :value="$checkpoint->code" required />
            <x-input name="name" label="Nama checkpoint" :value="$checkpoint->name" required />

            <div class="grid gap-4 sm:grid-cols-2">
                <x-input name="latitude" type="number" step="0.0000001" label="Latitude" :value="$checkpoint->latitude" required />
                <x-input name="longitude" type="number" step="0.0000001" label="Longitude" :value="$checkpoint->longitude" required />
            </div>

            <x-button>Simpan perubahan</x-button>
        </form>
    </x-card>
@endsection
