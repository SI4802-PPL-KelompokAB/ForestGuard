@extends('layouts.app')

@section('title', 'Pengelompokan Insiden')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <h1 class="text-2xl font-bold text-forest-950">Pengelompokan Insiden</h1>
            <p class="text-sm text-slate-600">Kelompok laporan berdasarkan jarak koordinat menggunakan Haversine.</p>
        </div>
        <form method="POST" action="{{ route('admin.incident-clusters.process') }}">
            @csrf
            <button type="submit" class="rounded-lg bg-forest-700 px-4 py-2 text-sm font-semibold text-white hover:bg-forest-800">
                Proses clustering
            </button>
        </form>
    </div>

    <x-card class="overflow-hidden p-0">
        <div class="border-b border-slate-200 px-4 py-3">
            <h2 class="font-semibold text-slate-900">Peta hasil clustering</h2>
            <p class="text-sm text-slate-600">Marker dengan warna sama berada dalam cluster yang sama. Penanda besar adalah centroid.</p>
        </div>
        <div id="incident-cluster-map"
             class="w-full"
             style="height: 420px; min-height: 420px;"
             aria-label="Peta hasil pengelompokan insiden"></div>
        <script id="incident-cluster-data" type="application/json">@json($mapClusters)</script>
    </x-card>

    <x-card class="overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                    <tr>
                        <th class="px-4 py-3">Cluster</th>
                        <th class="px-4 py-3">Centroid</th>
                        <th class="px-4 py-3">Radius</th>
                        <th class="px-4 py-3">Jumlah laporan</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Laporan anggota</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($clusters as $cluster)
                        <tr class="align-top">
                            <td class="px-4 py-3 font-semibold text-forest-800">#{{ $cluster->id }}</td>
                            <td class="whitespace-nowrap px-4 py-3 font-mono text-xs">
                                {{ number_format($cluster->centroid_latitude, 7) }},<br>
                                {{ number_format($cluster->centroid_longitude, 7) }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">{{ number_format($cluster->radius_km, 2) }} km</td>
                            <td class="px-4 py-3">{{ $cluster->report_count }}</td>
                            <td class="px-4 py-3">{{ $cluster->status }}</td>
                            <td class="max-w-md px-4 py-3">
                                <div class="space-y-1">
                                    @foreach ($cluster->incidents as $incident)
                                        <div>
                                            <span class="font-mono text-xs font-semibold text-forest-800">{{ $incident->code }}</span>
                                            <span class="text-slate-600">— {{ $incident->incidentType?->name ?? 'Tanpa jenis' }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-slate-500">Belum ada hasil clustering. Tekan “Proses clustering” untuk menjalankan pengelompokan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($clusters->hasPages())
            <div class="border-t border-slate-200 bg-slate-50 px-4 py-3">{{ $clusters->links() }}</div>
        @endif
    </x-card>
</div>
@endsection
