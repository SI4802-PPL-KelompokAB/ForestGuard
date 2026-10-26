@extends('layouts.app')

@section('title', 'Data Master - Tipe Insiden')

@section('content')
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold text-forest-900">Manajemen Data Master</h1>
            <p class="mt-1 text-sm text-slate-600">Kelola kategori dan jenis kejahatan atau perusakan hutan.</p>
        </div>
        <a href="{{ route('admin.master.incident-types.create') }}"
           class="inline-flex items-center justify-center rounded-lg bg-forest-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-forest-800">
            Tambah tipe insiden
        </a>
    </div>

    @include('admin.master._nav')

    <x-card class="mb-5">
        <form method="GET" action="{{ route('admin.master.incident-types.index') }}" class="grid gap-3 md:grid-cols-3">
            <div>
                <label for="q" class="mb-1 block text-sm font-medium text-slate-700">Cari</label>
                <input id="q" name="q" value="{{ $q }}" placeholder="Nama kategori insiden"
                       class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-forest-600 focus:outline-none focus:ring-2 focus:ring-forest-100">
            </div>
            <div>
                <label for="status" class="mb-1 block text-sm font-medium text-slate-700">Status</label>
                <select id="status" name="status"
                        class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-forest-600 focus:outline-none focus:ring-2 focus:ring-forest-100">
                    <option value="" @selected($status === '')>Semua status</option>
                    <option value="aktif" @selected($status === 'aktif')>Aktif</option>
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
                        <th class="px-4 py-3 font-medium">Nama Tipe Insiden</th>
                        <th class="px-4 py-3 font-medium">Laporan Terkait</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($incidentTypes as $type)
                        <tr class="border-t border-slate-100">
                            <td class="px-4 py-3 font-medium text-slate-800">{{ $type->name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $type->incidents_count }} laporan</td>
                            <td class="px-4 py-3">
                                @if ($type->trashed())
                                    <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">Terhapus</span>
                                @else
                                    <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">Aktif</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-2">
                                    @if ($type->trashed())
                                        <form method="POST" action="{{ route('admin.master.incident-types.restore', $type) }}">
                                            @csrf
                                            <button type="submit" class="text-sm font-medium text-forest-700 hover:underline">Pulihkan</button>
                                        </form>
                                    @else
                                        <a href="{{ route('admin.master.incident-types.edit', $type) }}" class="text-sm font-medium text-forest-700 hover:underline">Ubah</a>
                                        <form method="POST" action="{{ route('admin.master.incident-types.destroy', $type) }}"
                                              onsubmit="return confirm('Hapus tipe insiden ini? Tipe ini tidak akan muncul pada pelaporan baru.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-sm font-medium text-red-700 hover:underline">Hapus</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-slate-500">Tidak ada data tipe insiden.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($incidentTypes->hasPages())
            <div class="border-t border-slate-100 px-4 py-3">
                {{ $incidentTypes->links() }}
            </div>
        @endif
    </x-card>
@endsection
