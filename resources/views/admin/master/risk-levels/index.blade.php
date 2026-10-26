@extends('layouts.app')

@section('title', 'Data Master - Tingkat Risiko')

@section('content')
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold text-forest-900">Manajemen Data Master</h1>
            <p class="mt-1 text-sm text-slate-600">Kelola skala prioritas dan tingkat risiko penanganan insiden.</p>
        </div>
        <a href="{{ route('admin.master.risk-levels.create') }}"
           class="inline-flex items-center justify-center rounded-lg bg-forest-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-forest-800">
            Tambah tingkat risiko
        </a>
    </div>

    @include('admin.master._nav')

    <x-card class="mb-5">
        <form method="GET" action="{{ route('admin.master.risk-levels.index') }}" class="grid gap-3 md:grid-cols-3">
            <div>
                <label for="q" class="mb-1 block text-sm font-medium text-slate-700">Cari</label>
                <input id="q" name="q" value="{{ $q }}" placeholder="Nama tingkat risiko"
                       class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-forest-600 focus:outline-none focus:ring-2 focus:ring-forest-100">
            </div>
            <div>
                <label for="status" class="mb-1 block text-sm font-medium text-slate-700">Status</label>
                <select id="status" name="status"
                        class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-forest-600 focus:outline-none focus:ring-2 focus:ring-forest-100">
                    <option value="" @selected($status === '')>Semua status</option>
                    <option value="aktif" @selected($status === 'aktif')>Aktif</option>
                    <option value="nonaktif" @selected($status === 'nonaktif')>Nonaktif</option>
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
                        <th class="px-4 py-3 font-medium">Bobot / Urutan</th>
                        <th class="px-4 py-3 font-medium">Nama Tingkat Risiko</th>
                        <th class="px-4 py-3 font-medium">Laporan Terkait</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($riskLevels as $risk)
                        <tr class="border-t border-slate-100">
                            <td class="px-4 py-3 font-mono font-bold text-slate-700">#{{ $risk->sort_order }}</td>
                            <td class="px-4 py-3 font-medium text-slate-800">{{ $risk->name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $risk->incidents_count }} laporan</td>
                            <td class="px-4 py-3">
                                @if ($risk->is_active)
                                    <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">Aktif</span>
                                @else
                                    <span class="rounded bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">Nonaktif</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-3 items-center">
                                    <a href="{{ route('admin.master.risk-levels.edit', $risk) }}" class="text-sm font-medium text-forest-700 hover:underline">Ubah</a>
                                    
                                    <form method="POST" action="{{ route('admin.master.risk-levels.toggle', $risk) }}"
                                          onsubmit="return confirm('{{ $risk->is_active ? 'Nonaktifkan tingkat risiko ini? Risiko yang nonaktif tidak akan muncul di form laporan baru.' : 'Aktifkan kembali tingkat risiko ini?' }}')">
                                        @csrf
                                        <button type="submit" class="text-sm font-medium {{ $risk->is_active ? 'text-amber-700 hover:underline' : 'text-emerald-700 hover:underline' }}">
                                            {{ $risk->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-slate-500">Tidak ada data tingkat risiko.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($riskLevels->hasPages())
            <div class="border-t border-slate-100 px-4 py-3">
                {{ $riskLevels->links() }}
            </div>
        @endif
    </x-card>
@endsection
