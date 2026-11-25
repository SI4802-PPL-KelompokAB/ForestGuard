@extends('layouts.app')

@section('title', 'Ekspor Laporan PDF')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-forest-950">Ekspor Laporan PDF</h1>
        <p class="text-sm text-slate-600">Pilih filter untuk melihat dan mengunduh laporan insiden.</p>
    </div>

    <x-card>
        <form method="GET" action="{{ route('admin.reports.index') }}" class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
            <div>
                <label for="date_from" class="mb-1 block text-sm font-semibold text-slate-700">Tanggal awal</label>
                <input id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}" class="w-full rounded-lg border-slate-300">
            </div>
            <div>
                <label for="date_to" class="mb-1 block text-sm font-semibold text-slate-700">Tanggal akhir</label>
                <input id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}" class="w-full rounded-lg border-slate-300">
            </div>
            <div>
                <label for="status" class="mb-1 block text-sm font-semibold text-slate-700">Status</label>
                <select id="status" name="status" class="w-full rounded-lg border-slate-300">
                    <option value="">Semua status</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->name }}" @selected(($filters['status'] ?? '') === $status->name)>{{ $status->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="incident_type_id" class="mb-1 block text-sm font-semibold text-slate-700">Jenis insiden</label>
                <select id="incident_type_id" name="incident_type_id" class="w-full rounded-lg border-slate-300">
                    <option value="">Semua jenis</option>
                    @foreach ($incidentTypes as $type)
                        <option value="{{ $type->id }}" @selected((string) ($filters['incident_type_id'] ?? '') === (string) $type->id)>{{ $type->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="forest_area_id" class="mb-1 block text-sm font-semibold text-slate-700">Kawasan hutan</label>
                <select id="forest_area_id" name="forest_area_id" class="w-full rounded-lg border-slate-300">
                    <option value="">Semua kawasan</option>
                    @foreach ($forestAreas as $area)
                        <option value="{{ $area->id }}" @selected((string) ($filters['forest_area_id'] ?? '') === (string) $area->id)>{{ $area->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="rounded-lg bg-forest-700 px-4 py-2 text-sm font-semibold text-white hover:bg-forest-800">Terapkan Filter</button>
                <a href="{{ route('admin.reports.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset</a>
            </div>
        </form>
    </x-card>

    <div class="flex justify-end">
        <a href="{{ route('admin.reports.pdf', request()->query()) }}" class="inline-flex items-center rounded-lg bg-forest-700 px-4 py-2 text-sm font-semibold text-white hover:bg-forest-800">Ekspor PDF</a>
    </div>

    <x-card class="overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                    <tr>
                        <th class="px-4 py-3">Kode</th>
                        <th class="px-4 py-3">Jenis Insiden</th>
                        <th class="px-4 py-3">Kawasan</th>
                        <th class="px-4 py-3">Pelapor</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Waktu Kejadian</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($incidents as $incident)
                        <tr>
                            <td class="px-4 py-3 font-mono font-semibold text-forest-800">{{ $incident->code }}</td>
                            <td class="px-4 py-3">{{ $incident->incidentType?->name ?? '-' }}</td>
                            <td class="px-4 py-3">{{ $incident->forestArea?->name ?? '-' }}</td>
                            <td class="px-4 py-3">{{ $incident->reporter?->name ?? 'Anonim' }}</td>
                            <td class="px-4 py-3">{{ $incident->caseStatus?->name ?? '-' }}</td>
                            <td class="px-4 py-3">{{ $incident->occurred_at?->format('d M Y, H:i') ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-slate-500">Tidak ada laporan sesuai filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($incidents->hasPages())
            <div class="border-t border-slate-200 bg-slate-50 px-4 py-3">{{ $incidents->links() }}</div>
        @endif
    </x-card>
</div>
@endsection
