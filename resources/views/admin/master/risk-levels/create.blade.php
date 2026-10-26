@extends('layouts.app')

@section('title', 'Tambah Tingkat Risiko')

@section('content')
    <div class="mb-5">
        <a href="{{ route('admin.master.risk-levels.index') }}" class="text-sm text-forest-700 hover:underline">Kembali ke daftar tingkat risiko</a>
        <h1 class="mt-2 text-xl font-semibold text-forest-900">Tambah Tingkat Risiko</h1>
        <p class="mt-1 text-sm text-slate-600">Daftarkan skala prioritas penanganan insiden baru.</p>
    </div>

    <x-card class="max-w-xl">
        <form method="POST" action="{{ route('admin.master.risk-levels.store') }}" class="space-y-4">
            @csrf
            <x-input name="name" label="Nama tingkat risiko" required placeholder="Contoh: Darurat / Kritis" />
            <x-input name="sort_order" type="number" min="1" label="Bobot / Urutan Prioritas" required placeholder="Contoh: 1 (Urutan paling tinggi)" />

            <div>
                <label for="is_active" class="mb-1 block text-sm font-medium text-slate-700">Status Aktif</label>
                <select id="is_active" name="is_active" required
                        class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-forest-600 focus:outline-none focus:ring-2 focus:ring-forest-100">
                    <option value="1" @selected(old('is_active', '1') === '1')>Aktif (Dapat dipilih di modul laporan)</option>
                    <option value="0" @selected(old('is_active') === '0')>Nonaktif (Disembunyikan dari pilihan baru)</option>
                </select>
                @error('is_active')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <x-button>Simpan tingkat risiko</x-button>
        </form>
    </x-card>
@endsection
