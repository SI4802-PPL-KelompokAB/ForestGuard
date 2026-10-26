@extends('layouts.app')

@section('title', 'Tambah akun')

@section('content')
    <div class="mb-5">
        <a href="{{ route('admin.users.index') }}" class="text-sm text-forest-700 hover:underline">Kembali ke daftar pengguna</a>
        <h1 class="mt-2 text-xl font-semibold text-forest-900">Tambah akun</h1>
        <p class="mt-1 text-sm text-slate-600">Sistem akan membuat akun aktif dan mengirimkan kata sandi sementara ke alamat email pengguna.</p>
    </div>

    <x-card class="max-w-xl">
        <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-4">
            @csrf
            <x-input name="name" label="Nama lengkap" required autocomplete="name" />
            <x-input name="email" type="email" label="Email" required autocomplete="email" />
            <div>
                <label for="role" class="mb-1 block text-sm font-medium text-slate-700">Peran</label>
                <select id="role" name="role" required
                        class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-forest-600 focus:outline-none focus:ring-2 focus:ring-forest-100">
                    <option value="">Pilih peran</option>
                    @foreach ($roles as $value => $label)
                        <option value="{{ $value }}" @selected(old('role') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('role')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <x-button>Tambah akun</x-button>
        </form>
    </x-card>
@endsection
