@extends('layouts.app')

@section('title', 'Pengguna')

@section('content')
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold text-forest-900">Manajemen pengguna</h1>
            <p class="mt-1 text-sm text-slate-600">Kelola akun admin, ranger, dan warga.</p>
        </div>
        <a href="{{ route('admin.users.create') }}"
           class="inline-flex items-center justify-center rounded-lg bg-forest-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-forest-800">
            Tambah akun
        </a>
    </div>

    <x-card class="mb-5">
        <form method="GET" action="{{ route('admin.users.index') }}" class="grid gap-3 md:grid-cols-4">
            <div>
                <label for="q" class="mb-1 block text-sm font-medium text-slate-700">Cari</label>
                <input id="q" name="q" value="{{ $q }}" placeholder="Nama atau email"
                       class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-forest-600 focus:outline-none focus:ring-2 focus:ring-forest-100">
            </div>
            <div>
                <label for="peran" class="mb-1 block text-sm font-medium text-slate-700">Peran</label>
                <select id="peran" name="peran"
                        class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-forest-600 focus:outline-none focus:ring-2 focus:ring-forest-100">
                    <option value="">Semua peran</option>
                    @foreach (\App\Models\Role::options() as $value => $label)
                        <option value="{{ $value }}" @selected($peran === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="status" class="mb-1 block text-sm font-medium text-slate-700">Status</label>
                <select id="status" name="status"
                        class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-forest-600 focus:outline-none focus:ring-2 focus:ring-forest-100">
                    <option value="" @selected($status === '')>Tidak terhapus</option>
                    <option value="aktif" @selected($status === 'aktif')>Aktif</option>
                    <option value="nonaktif" @selected($status === 'nonaktif')>Nonaktif</option>
                    <option value="terhapus" @selected($status === 'terhapus')>Terhapus</option>
                </select>
            </div>
            <div class="flex items-end">
                <x-button type="submit">Terapkan</x-button>
            </div>
        </form>
    </x-card>

    <x-card class="overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-4 py-3 font-medium">Nama</th>
                        <th class="px-4 py-3 font-medium">Email</th>
                        <th class="px-4 py-3 font-medium">Peran</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $managedUser)
                        <tr class="border-t border-slate-100">
                            <td class="px-4 py-3 font-medium text-slate-800">{{ $managedUser->name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $managedUser->email }}</td>
                            <td class="px-4 py-3">{{ $managedUser->role?->label() }}</td>
                            <td class="px-4 py-3">
                                @if ($managedUser->trashed())
                                    <span class="text-slate-500">Terhapus</span>
                                @elseif ($managedUser->is_active)
                                    <span class="text-forest-700">Aktif</span>
                                @else
                                    <span class="text-amber-700">Nonaktif</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-2">
                                    @if ($managedUser->trashed())
                                        <form method="POST" action="{{ route('admin.users.restore', $managedUser) }}">
                                            @csrf
                                            <button type="submit" class="text-sm font-medium text-forest-700 hover:underline">Pulihkan</button>
                                        </form>
                                    @else
                                        <a href="{{ route('admin.users.edit', $managedUser) }}" class="text-sm font-medium text-forest-700 hover:underline">Ubah</a>
                                        @if (auth()->id() !== $managedUser->id)
                                            <form method="POST" action="{{ route('admin.users.destroy', $managedUser) }}"
                                                  onsubmit="return confirm('Hapus pengguna ini? Akun dapat dipulihkan nanti.')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-sm font-medium text-red-700 hover:underline">Hapus</button>
                                            </form>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-slate-500">Tidak ada pengguna yang sesuai filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($users->hasPages())
            <div class="border-t border-slate-100 px-4 py-3">
                {{ $users->links() }}
            </div>
        @endif
    </x-card>
@endsection
