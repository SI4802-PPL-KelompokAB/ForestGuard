@extends('layouts.app')

@section('title', 'Manajemen Kasus Insiden')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-forest-950">Kasus Insiden Hutan</h1>
            <p class="text-sm text-slate-600">Verifikasi, penugasan, pemantauan, dan penyelesaian laporan ancaman hutan.</p>
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    {{-- Filter Status Tabs --}}
    <div class="flex items-center gap-2 overflow-x-auto pb-2">
        <a href="{{ route('admin.incidents.index') }}"
           class="px-3 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap {{ empty($statusFilter) ? 'bg-forest-800 text-white' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' }}">
            Semua Kasus
        </a>
        @foreach ($statuses as $st)
            <a href="{{ route('admin.incidents.index', ['status' => $st->name]) }}"
               class="px-3 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap {{ $statusFilter === $st->name ? 'bg-forest-800 text-white' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' }}">
                {{ $st->name }}
            </a>
        @endforeach
    </div>

    {{-- Tabel Kasus --}}
    <x-card class="overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-slate-600 text-xs uppercase tracking-wider font-semibold">
                    <tr>
                        <th class="px-4 py-3 text-left">Kode</th>
                        <th class="px-4 py-3 text-left">Jenis Insiden</th>
                        <th class="px-4 py-3 text-left">Kawasan</th>
                        <th class="px-4 py-3 text-left">Pelapor</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-left">Waktu Kejadian</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($incidents as $incident)
                        <tr class="hover:bg-slate-50 transition">
                            <td class="px-4 py-3 font-mono font-bold text-forest-800">{{ $incident->code }}</td>
                            <td class="px-4 py-3 font-medium text-slate-800">{{ $incident->incidentType?->name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $incident->forestArea?->name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $incident->reporter?->name ?? 'Anonim' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-800">
                                    {{ $incident->caseStatus?->name }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-500 text-xs">{{ $incident->occurred_at?->format('d M Y, H:i') }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('admin.incidents.show', $incident) }}" class="inline-flex items-center px-3 py-1 text-xs font-semibold text-forest-700 bg-forest-50 border border-forest-200 rounded hover:bg-forest-100">
                                    Buka Kasus
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-slate-500">
                                Belum ada laporan insiden yang tercatat.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($incidents->hasPages())
            <div class="px-4 py-3 border-t border-slate-200 bg-slate-50">
                {{ $incidents->links() }}
            </div>
        @endif
    </x-card>
</div>
@endsection

