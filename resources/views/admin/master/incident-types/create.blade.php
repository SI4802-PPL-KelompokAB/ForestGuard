@extends('layouts.app')

@section('title', 'Tambah Tipe Insiden')

@section('content')
    <div class="mb-5">
        <a href="{{ route('admin.master.incident-types.index') }}" class="text-sm text-forest-700 hover:underline">Kembali ke daftar tipe insiden</a>
        <h1 class="mt-2 text-xl font-semibold text-forest-900">Tambah Tipe Insiden</h1>
        <p class="mt-1 text-sm text-slate-600">Daftarkan kategori jenis pelanggaran atau perusakan hutan baru.</p>
    </div>

    <x-card class="max-w-xl">
        <form method="POST" action="{{ route('admin.master.incident-types.store') }}" class="space-y-4">
            @csrf
            <x-input name="name" label="Nama tipe insiden" required placeholder="Contoh: Penebangan Liar (Illegal Logging)" />
            <x-button>Simpan tipe insiden</x-button>
        </form>
    </x-card>
@endsection
