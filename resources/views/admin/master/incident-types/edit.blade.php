@extends('layouts.app')

@section('title', 'Ubah Tipe Insiden')

@section('content')
    <div class="mb-5">
        <a href="{{ route('admin.master.incident-types.index') }}" class="text-sm text-forest-700 hover:underline">Kembali ke daftar tipe insiden</a>
        <h1 class="mt-2 text-xl font-semibold text-forest-900">Ubah Tipe Insiden</h1>
        <p class="mt-1 text-sm text-slate-600">Perbarui nama kategori perusakan hutan.</p>
    </div>

    <x-card class="max-w-xl">
        <form method="POST" action="{{ route('admin.master.incident-types.update', $incidentType) }}" class="space-y-4">
            @csrf
            @method('PUT')
            <x-input name="name" label="Nama tipe insiden" :value="$incidentType->name" required />
            <x-button>Simpan perubahan</x-button>
        </form>
    </x-card>
@endsection
