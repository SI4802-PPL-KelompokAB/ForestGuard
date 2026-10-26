@extends('layouts.app')

@section('title', 'Ubah pengguna')

@section('content')
    @php
        $isSelf = auth()->id() === $managedUser->id;
    @endphp

    <div class="mb-5">
        <a href="{{ route('admin.users.index') }}" class="text-sm text-forest-700 hover:underline">Kembali ke daftar pengguna</a>
        <h1 class="mt-2 text-xl font-semibold text-forest-900">Ubah pengguna</h1>
        <p class="mt-1 text-sm text-slate-600">Email tidak dapat diubah. Kosongkan kata sandi jika tidak ingin mengganti.</p>
    </div>

    <x-card class="max-w-xl">
        <form method="POST" action="{{ route('admin.users.update', $managedUser) }}" class="space-y-4">
            @csrf
            @method('PUT')
            <x-input name="name" label="Nama lengkap" :value="$managedUser->name" required autocomplete="name" />

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Email</label>
                <p class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">{{ $managedUser->email }}</p>
            </div>

            <x-input name="password" type="password" label="Kata sandi baru (opsional)" autocomplete="new-password" />
            <x-input name="password_confirmation" type="password" label="Konfirmasi kata sandi baru" autocomplete="new-password" />

            @if ($isSelf)
                <input type="hidden" name="role" value="{{ $managedUser->role?->name }}">
                <input type="hidden" name="is_active" value="1">
            @endif

            <div>
                <label for="role" class="mb-1 block text-sm font-medium text-slate-700">Peran</label>
                <select id="role" name="role" @disabled($isSelf) required
                        class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-forest-600 focus:outline-none focus:ring-2 focus:ring-forest-100 disabled:bg-slate-50">
                    @foreach ($roles as $value => $label)
                        <option value="{{ $value }}" @selected(old('role', $managedUser->role?->name) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('role')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
                @if ($isSelf)
                    <p class="mt-1 text-xs text-slate-500">Anda tidak dapat mengubah peran akun sendiri.</p>
                @endif
            </div>

            <div>
                <label for="is_active" class="mb-1 block text-sm font-medium text-slate-700">Status aktif</label>
                <select id="is_active" name="is_active" @disabled($isSelf) required
                        class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-forest-600 focus:outline-none focus:ring-2 focus:ring-forest-100 disabled:bg-slate-50">
                    <option value="1" @selected((string) old('is_active', $managedUser->is_active ? '1' : '0') === '1')>Aktif</option>
                    <option value="0" @selected((string) old('is_active', $managedUser->is_active ? '1' : '0') === '0')>Nonaktif</option>
                </select>
                @error('is_active')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
                @if ($isSelf)
                    <p class="mt-1 text-xs text-slate-500">Anda tidak dapat menonaktifkan akun sendiri.</p>
                @endif
            </div>

            <x-button>Simpan perubahan</x-button>
        </form>
    </x-card>
@endsection
