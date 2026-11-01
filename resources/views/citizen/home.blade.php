@extends('layouts.app')

@section('title', 'Laporan Saya')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-forest-950">Laporan Saya</h1>
            <p class="text-sm text-slate-600">Pantau perkembangan laporan insiden dan dugaan perusakan hutan yang Anda kirimkan.</p>
        </div>
        <a href="{{ route('citizen.incidents.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-forest-700 text-white font-semibold text-sm rounded-lg hover:bg-forest-800 shadow-sm transition">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Buat Laporan Baru
        </a>
    </div>

    @if (session('status'))
        <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    <div class="space-y-4">
        @forelse ($incidents as $incident)
            <a href="{{ route('citizen.incidents.show', $incident) }}" class="block p-5 bg-white border border-slate-200 rounded-xl hover:border-forest-600 hover:shadow-sm transition">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-mono font-bold text-forest-800 text-sm">{{ $incident->code }}</span>
                            <span class="font-semibold text-slate-900 text-base">{{ $incident->incidentType?->name }}</span>
                            <span class="text-slate-400">·</span>
                            <span class="text-sm text-slate-600">{{ $incident->forestArea?->name }}</span>
                        </div>
                        <p class="mt-1 text-sm text-slate-600 line-clamp-2">{{ $incident->description }}</p>
                    </div>
                    <div class="flex sm:flex-col items-center sm:items-end justify-between gap-1">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-800">
                            {{ $incident->caseStatus?->name }}
                        </span>
                        <span class="text-xs text-slate-400">{{ $incident->occurred_at?->format('d M Y, H:i') }}</span>
                    </div>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100">
                    <x-incident-progress :incident="$incident" />
                </div>
            </a>
        @empty
            <x-card class="text-center py-12">
                <p class="text-slate-500 text-sm mb-4">Anda belum pernah mengirimkan laporan insiden.</p>
                <a href="{{ route('citizen.incidents.create') }}" class="inline-flex items-center gap-1.5 px-4 py-2 bg-forest-700 text-white font-semibold text-sm rounded-lg hover:bg-forest-800">
                    Mulai Buat Laporan Pertama
                </a>
            </x-card>
        @endforelse
    </div>
</div>
@endsection
