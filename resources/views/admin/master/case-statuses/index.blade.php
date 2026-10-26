@extends('layouts.app')

@section('title', 'Data Master - Status Kasus')

@section('content')
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold text-forest-900">Manajemen Data Master</h1>
            <p class="mt-1 text-sm text-slate-600">Daftar status penanganan kasus dan laporan insiden (Data Referensi Sistem Tetap).</p>
        </div>
    </div>

    @include('admin.master._nav')

    <x-alert type="info" class="mb-5">
        <strong>Informasi Sistem:</strong> Status kasus adalah kamus alur bisnis inti yang dikunci oleh sistem untuk menjamin integritas riwayat dan transisi status insiden. Data ini bersifat <strong>Read-Only</strong> (hanya dapat dilihat) dan tidak dapat ditambah, diubah, maupun dihapus.
    </x-alert>

    <x-card class="overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-4 py-3 font-medium">Urutan Alur</th>
                        <th class="px-4 py-3 font-medium">Nama Status Kasus</th>
                        <th class="px-4 py-3 font-medium">Kategori Alur</th>
                        <th class="px-4 py-3 font-medium">Laporan Terkait</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($caseStatuses as $status)
                        <tr class="border-t border-slate-100">
                            <td class="px-4 py-3 font-mono font-bold text-slate-700">Tahap {{ $status->sequence }}</td>
                            <td class="px-4 py-3 font-medium text-slate-800">{{ $status->name }}</td>
                            <td class="px-4 py-3">
                                @if ($status->is_terminal)
                                    <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">Status Akhir (Terminal)</span>
                                @else
                                    <span class="rounded bg-sky-100 px-2 py-0.5 text-xs font-medium text-sky-800">Status Berjalan (In Progress)</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $status->incidents_count }} laporan</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-slate-500">Tidak ada data status kasus.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($caseStatuses->hasPages())
            <div class="border-t border-slate-100 px-4 py-3">
                {{ $caseStatuses->links() }}
            </div>
        @endif
    </x-card>
@endsection
